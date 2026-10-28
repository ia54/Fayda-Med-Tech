import test from 'node:test';
import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
import ts from 'typescript';
import {zodResolver} from '@hookform/resolvers/zod';
async function schema(path) {
 const source=readFileSync(new URL(path,import.meta.url),'utf8');
 const js=ts.transpileModule(source,{compilerOptions:{module:ts.ModuleKind.ESNext,target:ts.ScriptTarget.ES2022}}).outputText.replace(/from ["']zod["']/g,`from ${JSON.stringify(import.meta.resolve('zod'))}`);
 return import(`data:text/javascript;base64,${Buffer.from(js).toString('base64')}`);
}
const {createUserSchema,updateUserSchema}=await schema('../lib/validations/user.ts');
const {organizationSchema}=await schema('../lib/validations/organization.ts');
const {testimonialValidationSchemas}=await schema('../app/schemas/testimonial.schema.ts');
const {homepageValidationSchemas}=await schema('../app/schemas/homepage.schema.ts');
const {aboutValidationSchemas}=await schema('../app/schemas/about.schema.ts');
const validUser={first_name:'Synthetic',last_name:'Tester',email:'synthetic@example.invalid',password:'SyntheticOnly123',organization_id:1,role:'pharmacist',status:'active'};
test('user resolver maps valid data and required/type/password failures',async()=>{
 const resolve=zodResolver(createUserSchema),options={criteriaMode:'all',shouldUseNativeValidation:false,fields:{}};
 const valid=await resolve(validUser,undefined,options);assert.deepEqual(valid.errors,{});assert.equal(valid.values.organization_id,1);
 for(const [organization_id,message] of [[undefined,'Organization is required'],['1','Organization must be selected']]){
  const invalid=await resolve({...validUser,organization_id,password:'short'},undefined,options);
  assert.equal(invalid.errors.organization_id.message,message);assert.ok(invalid.errors.password);assert.deepEqual(invalid.values,{});
 }
 assert.equal(updateUserSchema.safeParse({...validUser,password:''}).success,true);
});
test('organization enums and testimonial boolean retain validation',()=>{
 const organization={org_name:'Synthetic',org_type:'Pharmacy',subscription_plan:'Free',email:'synthetic@example.invalid'};
 assert.equal(organizationSchema.safeParse(organization).success,true);
 const missing=organizationSchema.safeParse({...organization,org_type:undefined});assert.equal(missing.success,false);assert.equal(missing.error.issues[0].message,'Required');
 assert.equal(organizationSchema.safeParse({...organization,org_type:'arbitrary'}).success,false);
 const testimonial={rating:'5',comment:'Synthetic',commented_by:'Synthetic',position:'Tester',status:true};
 assert.equal(testimonialValidationSchemas.testimonialValidationSchema.safeParse(testimonial).success,true);
 const invalid=testimonialValidationSchemas.testimonialValidationSchema.safeParse({...testimonial,status:'true'});assert.equal(invalid.success,false);assert.equal(invalid.error.issues[0].message,'Status must be a boolean!');
});
test('CMS list schemas retain edited entries and reject invalid entries',()=>{
 for(const [definition,key,base] of [
  [homepageValidationSchemas.whyNowValidationSchema,'whyNowRightList',{homeWhyNowLeftTitle1:'One',homeWhyNowLeftTitle2:'Two',homeWhyNowRightTitle:'Right',homeWhyNowRightListTitle:'List'}],
  [aboutValidationSchemas.problemWeSolveSchema,'aboutProblemWeSolveRightList',{aboutProblemWeSolveSectionTitle:'Title',aboutProblemWeSolveSectionDescription:'Description',aboutProblemWeSolveRightTopDescription:'Details'}],
 ]){
  assert.deepEqual(definition.parse({...base,[key]:[{value:'Synthetic edited entry'}]})[key],[{value:'Synthetic edited entry'}]);
  assert.equal(definition.safeParse({...base,[key]:[{value:123}]}).success,false);assert.equal(definition.safeParse(base).success,true);
 }
});

test('oversized constrained collections fail before validating every element',async()=>{
 const {z}=await import('zod');let checked=0;
 const bounded=z.array(z.string().superRefine(()=>{checked++;})).max(2);
 const result=bounded.safeParse(Array(10000).fill('Synthetic'));
 assert.equal(result.success,false);assert.equal(result.error.issues.length,1);assert.equal(result.error.issues[0].code,'too_big');assert.equal(checked,0);
 assert.equal(bounded.safeParse(['one','two']).success,true);assert.equal(checked,2);
});
